<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function () {
            // Prevent inserts between reading the highest ID and advancing the sequence.
            DB::statement('LOCK TABLE customers IN SHARE ROW EXCLUSIVE MODE');
            DB::unprepared(<<<'SQL'
                DO $$
                DECLARE
                    customer_sequence regclass;
                    maximum_id bigint;
                    sequence_value bigint;
                    sequence_called boolean;
                BEGIN
                    customer_sequence := pg_get_serial_sequence('customers', 'id')::regclass;

                    -- Restored databases may keep the nextval default without sequence ownership.
                    IF customer_sequence IS NULL THEN
                        SELECT sequence.oid::regclass INTO customer_sequence
                        FROM pg_attrdef AS column_default
                        JOIN pg_attribute AS attribute
                          ON attribute.attrelid = column_default.adrelid
                         AND attribute.attnum = column_default.adnum
                        JOIN pg_depend AS dependency
                          ON dependency.classid = 'pg_attrdef'::regclass
                         AND dependency.objid = column_default.oid
                         AND dependency.refclassid = 'pg_class'::regclass
                        JOIN pg_class AS sequence
                          ON sequence.oid = dependency.refobjid
                         AND sequence.relkind = 'S'
                        WHERE column_default.adrelid = 'customers'::regclass
                          AND attribute.attname = 'id';
                    END IF;

                    IF customer_sequence IS NULL THEN
                        RAISE EXCEPTION 'Cannot find the customers.id sequence; inspect its column default before retrying.';
                    END IF;

                    SELECT MAX(id) INTO maximum_id FROM customers;
                    EXECUTE format('SELECT last_value, is_called FROM %s', customer_sequence)
                        INTO sequence_value, sequence_called;

                    -- Never rewind a sequence that is already ahead of the stored IDs.
                    IF maximum_id IS NOT NULL AND
                        (maximum_id > sequence_value OR (maximum_id = sequence_value AND NOT sequence_called)) THEN
                        PERFORM setval(customer_sequence, maximum_id, true);
                    END IF;
                END
                $$;
                SQL);
        });
    }

    public function down(): void
    {
        // Rewinding the sequence could reuse an existing customer ID.
    }
};
