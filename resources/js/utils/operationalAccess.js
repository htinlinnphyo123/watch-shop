export const isOperationalRole = role => ['staff', 'manager'].includes(role);

export const canManageOrder = (user, order) => !isOperationalRole(user?.role)
    || (user?.id != null && order?.user_id != null && String(user.id) === String(order.user_id));
