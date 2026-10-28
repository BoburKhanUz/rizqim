// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/post_v1_orders_response.dart';
import '../models/post_v1_orders_uuid_assign_courier_response.dart';
import '../models/post_v1_orders_uuid_cancel_response.dart';
import '../models/post_v1_orders_uuid_complete_response.dart';
import '../models/post_v1_orders_uuid_status_response.dart';
import '../models/scope.dart';
import '../models/v1_orders_request_body.dart';
import '../models/v1_orders_uuid_assign_courier_request_body.dart';
import '../models/v1_orders_uuid_cancel_request_body.dart';
import '../models/v1_orders_uuid_status_request_body.dart';

part 'orders_api.g.dart';

@RestApi()
abstract class OrdersApi {
  factory OrdersApi(Dio dio, {String? baseUrl}) = _OrdersApi;

  /// List orders.
  ///
  /// The orders the user may see: a customer's own, a courier's deliveries, the store's (owner) or the branch's.
  /// (manager), newest first, with the relations the apps show. Without `scope`: every order, unpaginated (kept for.
  /// app versions from before scopes). `scope=active`: the orders still in progress (at most 200; `meta.truncated`.
  /// says more exist). `scope=history`: finished orders (delivered, canceled, rejected, refunded), keyset-paginated:.
  /// send `meta.next_cursor` back as `cursor` until it is null.
  ///
  /// [scope] - Omit for the full legacy list.
  ///
  /// [perPage] - Only with `scope=history`: page size, 1-50 (larger values are capped at 50).
  ///
  /// [cursor] - Only with `scope=history`: `meta.next_cursor` of the previous page.
  @GET('/v1/orders')
  Future<dynamic> orderIndex({
    @Query('per_page') int? perPage = 20,
    @Query('scope') Scope? scope,
    @Query('cursor') String? cursor,
  });

  /// Place an order (checkout).
  ///
  /// Prices, stock, the promo code and the delivery fee are checked on the server. `payment_url` is set for online.
  /// payment methods (Click, Payme): open it to pay; the order is cancelled when it stays unpaid. 400 with the reason.
  /// when the order cannot be placed (store closed, item unavailable, minimum not reached...).
  ///
  /// P1-21 (docs/MENU.md): `items.*.quantity` may be a fraction for items sold by weight (kg/l, at least.
  /// `min_quantity`, in `quantity_step` steps); `items.*.modifiers` lists the chosen modifiers as uuids or.
  /// `{id: uuid, quantity}`; prices always come from the server. 422 when a modifier rule is broken, the item is on.
  /// the branch's stop-list, its batch is sold out ("tugadi") or it is hidden on the channel. The channel is decided by.
  /// the server from the sign-in (P1-34): `mobile` for a token of a customer mobile app device, else `telegram`; a.
  /// `channel` in the body is accepted for older apps but ignored. `type=dine_in` is refused with 422 (hall orders.
  /// are taken by staff on the POS). An `Idempotency-Key` header makes retries safe: the same key with the same body.
  /// within 24 hours returns the first response (header `Idempotent-Replayed: true`), with another body 409.
  ///
  /// P1-32 (docs/ZONES.md): when the store has delivery zones, a `delivery` order is routed and priced by the zone.
  /// that covers `latitude`/`longitude`, exactly as `GET /delivery/quote` prices it: the zone's fee (free past its.
  /// threshold) and minimum order replace the store-wide ones, any fee the client sends is ignored. Without.
  /// `branch_id` the branch is picked automatically (the zone's branch, else the nearest open one); with it, that.
  /// branch must serve a zone covering the point. 422 with `data.reason`: `outside_zone`, `branch_outside_zone`,.
  /// `branch_closed`, or `below_minimum_order` (with `data.min_order_amount` and `data.subtotal`). Stores without.
  /// zones keep the branch radius rule and the store's delivery fee.
  ///
  /// P4-32: a payment provider the RIZQIM team switched off (Tizim → To'lov tizimlari) is a 422 with `code` and.
  /// `data.reason` `payment_provider_inactive` (and `errors.payment_method`); the store's payment settings already.
  /// show it `{enabled: false, available: false}`. In test mode a Payme `payment_url` opens Payme's sandbox checkout.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/orders')
  Future<PostV1OrdersResponse> orderStore({
    @Body() required V1OrdersRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Assign a courier to an order (orders.assign_courier).
  ///
  /// 403/422 when the order's status does not allow it or the courier is not the store's. P2-30: by permission.
  /// (orders.assign_courier with orders.manage on this order: OrderPolicy::assignCourier), not by role.
  @POST('/v1/orders/{uuid}/assign-courier')
  Future<PostV1OrdersUuidAssignCourierResponse> orderAssignCourier({
    @Path('uuid') required String uuid,
    @Body() required V1OrdersUuidAssignCourierRequestBody body,
  });

  /// Cancel own order (customer).
  ///
  /// The customer cancels their own order: only while it is new and unpaid (Order::isCancellableByCustomer), always.
  /// with the reason customer_request. Stock, promo use and the like come back as with any cancellation; a payment.
  /// arriving afterwards is refused by the payment webhooks. Returns the order as GET /orders lists it.
  @POST('/v1/orders/{uuid}/cancel')
  Future<PostV1OrdersUuidCancelResponse> orderCancel({
    @Path('uuid') required String uuid,
    @Body() V1OrdersUuidCancelRequestBody? body,
  });

  /// Mark an order delivered (courier).
  ///
  /// 403/422 when the order's status or the user's role does not allow it.
  @POST('/v1/orders/{uuid}/complete')
  Future<PostV1OrdersUuidCompleteResponse> orderComplete({
    @Path('uuid') required String uuid,
  });

  /// Change an order's status (owner, manager, courier).
  ///
  /// Only the transitions the order's state machine allows; cancelling or rejecting needs a `reason`. Answers 200.
  /// with the unchanged order when it is already in that status; 403/422 when the transition is not allowed.
  @POST('/v1/orders/{uuid}/status')
  Future<PostV1OrdersUuidStatusResponse> orderUpdateStatus({
    @Path('uuid') required String uuid,
    @Body() required V1OrdersUuidStatusRequestBody body,
  });
}
