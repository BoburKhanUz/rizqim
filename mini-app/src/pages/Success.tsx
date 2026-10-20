import { useLocation, Navigate } from 'react-router-dom';
import { OrderPlaced } from '../components/catalog/OrderPlaced';

// The order the cart just created ("/success" is opened with its uuid as router state)
function getOrderUuidFromState(state: unknown): string | undefined {
  if (typeof state === 'object' && state !== null && 'order_uuid' in state && typeof state.order_uuid === 'string') {
    return state.order_uuid;
  }
  return undefined;
}

// The confirmation of an order opened by id. It says only what is known: the order is placed and its number; the
// progress of the order is on the orders screen (no made-up steps, courier or arrival time here).
export default function Success() {
  const location = useLocation();
  const orderUuid = getOrderUuidFromState(location.state);

  if (!orderUuid && import.meta.env.PROD) {
    return <Navigate to="/" replace />;
  }

  // The short code when the cart passed it (state.order_number), else the start of the uuid
  const orderNumber = (location.state as { order_number?: unknown } | null)?.order_number;
  const orderId = (typeof orderNumber === 'string' && orderNumber.trim()) || orderUuid?.split('-')[0]?.toUpperCase() || undefined;

  return <OrderPlaced orderNumber={orderId} />;
}
