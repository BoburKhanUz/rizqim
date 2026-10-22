// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_pos_bookings_uuid_items_item_response.dart';
import '../models/get_v1_pos_bookings_calendar_response.dart';
import '../models/get_v1_pos_bookings_response.dart';
import '../models/get_v1_pos_bookings_uuid_response.dart';
import '../models/patch_v1_pos_bookings_uuid_items_item_response.dart';
import '../models/patch_v1_pos_bookings_uuid_response.dart';
import '../models/post_v1_pos_bookings_response.dart';
import '../models/post_v1_pos_bookings_uuid_arrive_response.dart';
import '../models/post_v1_pos_bookings_uuid_cancel_response.dart';
import '../models/post_v1_pos_bookings_uuid_confirm_response.dart';
import '../models/post_v1_pos_bookings_uuid_deposits_payment_refund_response.dart';
import '../models/post_v1_pos_bookings_uuid_deposits_payment_retain_response.dart';
import '../models/post_v1_pos_bookings_uuid_deposits_response.dart';
import '../models/post_v1_pos_bookings_uuid_items_response.dart';
import '../models/post_v1_pos_bookings_uuid_no_show_response.dart';
import '../models/v1_pos_bookings_request_body.dart';
import '../models/v1_pos_bookings_uuid_arrive_request_body.dart';
import '../models/v1_pos_bookings_uuid_cancel_request_body.dart';
import '../models/v1_pos_bookings_uuid_deposits_request_body.dart';
import '../models/v1_pos_bookings_uuid_items_item_request_body.dart';
import '../models/v1_pos_bookings_uuid_items_request_body.dart';
import '../models/v1_pos_bookings_uuid_request_body.dart';

part 'pos_bookings_api.g.dart';

@RestApi()
abstract class PosBookingsApi {
  factory PosBookingsApi(Dio dio, {String? baseUrl}) = _PosBookingsApi;

  /// Bookings (list, search by phone).
  ///
  /// Without `phone`: the bookings of the branch(es) between `from` and `to` (default today), by start time. With.
  /// `phone` (at least 4 digits, any form: "90 123 45 67"): the guest's or group's history across dates, newest.
  /// first. Filter by `status`. At most 200.
  ///
  /// [branchId] - One branch (default: the caller's; the whole store for store-wide staff).
  ///
  /// [phone] - Search by the contact phone (digits, at least 4).
  ///
  /// [status] - requested, confirmed, arrived, completed, canceled or no_show.
  ///
  /// [from] - First day, Y-m-d (store time zone).
  ///
  /// [to] - Last day, Y-m-d, inclusive.
  @GET('/v1/pos/bookings')
  Future<GetV1PosBookingsResponse> bookingIndex({
    @Query('branch_id') int? branchId,
    @Query('phone') String? phone,
    @Query('status') String? status,
    @Query('from') String? from,
    @Query('to') String? to,
  });

  /// Create a booking.
  ///
  /// A room or table of the branch (`dining_table_id`; a banquet adds the merged rooms in `extra_table_ids`) for.
  /// `starts_at`–`ends_at` (ISO 8601; without an offset: the store's time zone; without `ends_at`: the branch's default.
  /// slot, 5 hours). 409 `booking_conflict` with `data.conflicts` when a room is already booked then. `confirm: true`.
  /// creates it confirmed. The phone is stored as +998XXXXXXXXX.
  @POST('/v1/pos/bookings')
  Future<PostV1PosBookingsResponse> bookingStore({
    @Body() required V1PosBookingsRequestBody body,
  });

  /// Booking calendar (rooms × bookings).
  ///
  /// The branch's rooms and tables (floor order), each with the bookings that occupy it between `from` and `to`.
  /// (dates in the store's time zone, `to` inclusive, at most 31 days; default today). A banquet merging rooms is.
  /// listed under each of them; bookings without a room are in `unassigned`. Canceled bookings only with.
  /// `include_canceled=1`.
  ///
  /// [branchId] - The branch (default: the one the caller works in).
  ///
  /// [from] - First day, Y-m-d (store time zone).
  ///
  /// [to] - Last day, Y-m-d, inclusive.
  ///
  /// [includeCanceled] - Also canceled bookings.
  @GET('/v1/pos/bookings/calendar')
  Future<GetV1PosBookingsCalendarResponse> bookingCalendar({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('include_canceled') bool? includeCanceled,
  });

  /// A booking
  @GET('/v1/pos/bookings/{uuid}')
  Future<GetV1PosBookingsUuidResponse> bookingShow({
    @Path('uuid') required String uuid,
  });

  /// Change a booking.
  ///
  /// Only the fields sent change (a requested or confirmed booking). Moving it or changing its rooms is checked for.
  /// conflicts again (409). `version` (optional): the one read; another device changed it meanwhile: 409.
  @PATCH('/v1/pos/bookings/{uuid}')
  Future<PatchV1PosBookingsUuidResponse> bookingUpdate({
    @Path('uuid') required String uuid,
    @Body() V1PosBookingsUuidRequestBody? body,
  });

  /// "Keldi": the guests arrived.
  ///
  /// Opens the order of the booked room (channel pos_hall) with `guests` (default: the planned number) and the.
  /// pre-ordered dishes on hold with their due times; the deposits become the order's prepayment. `dining_table_id`.
  /// seats them in another room of the branch. 409 when the order is already open (`data.order_uuid`).
  @POST('/v1/pos/bookings/{uuid}/arrive')
  Future<PostV1PosBookingsUuidArriveResponse> bookingArrive({
    @Path('uuid') required String uuid,
    @Body() V1PosBookingsUuidArriveRequestBody? body,
  });

  /// Cancel a booking.
  ///
  /// `reason` is required. Taken deposits stay: refund or retain them afterwards (`data.deposit_unsettled`).
  @POST('/v1/pos/bookings/{uuid}/cancel')
  Future<PostV1PosBookingsUuidCancelResponse> bookingCancel({
    @Path('uuid') required String uuid,
    @Body() required V1PosBookingsUuidCancelRequestBody body,
  });

  /// Confirm a booking
  @POST('/v1/pos/bookings/{uuid}/confirm')
  Future<PostV1PosBookingsUuidConfirmResponse> bookingConfirm({
    @Path('uuid') required String uuid,
  });

  /// Take a deposit.
  ///
  /// `amount`, `tender` cash|card|qr (`card_type`, `transaction_id` for card and QR). Issues an ADVANCE receipt.
  /// (`fiscal_receipt_uuid`, `fiscal_status`). The `Idempotency-Key` header is required: a retry returns the same.
  /// deposit and never a second receipt. A requested booking becomes confirmed.
  @POST('/v1/pos/bookings/{uuid}/deposits')
  Future<PostV1PosBookingsUuidDepositsResponse> bookingTakeDeposit({
    @Path('uuid') required String uuid,
    @Body() required V1PosBookingsUuidDepositsRequestBody body,
  });

  /// Refund a deposit.
  ///
  /// What is left of it goes back to the guest. `reason` required. Needs pos.refund: a cashier sends a manager's.
  /// one-time `approval_token` (POST /pos/approvals). By default no receipt is issued (the refund is documented with.
  /// a refund act; a store may switch on a REFUND receipt). Not while the guests are seated (arrived).
  @POST('/v1/pos/bookings/{uuid}/deposits/{payment}/refund')
  Future<PostV1PosBookingsUuidDepositsPaymentRefundResponse>
  bookingRefundDeposit({
    @Path('uuid') required String uuid,
    @Path('payment') required String payment,
  });

  /// Retain a deposit.
  ///
  /// After a cancellation or a no-show (or what is left once the bill is closed) the deposit is kept. `reason`.
  /// required. Needs orders.cancel: a cashier sends a manager's `approval_token`. Issues a SALE receipt for the booking.
  /// service paid from the advance (a store may switch it off).
  @POST('/v1/pos/bookings/{uuid}/deposits/{payment}/retain')
  Future<PostV1PosBookingsUuidDepositsPaymentRetainResponse>
  bookingRetainDeposit({
    @Path('uuid') required String uuid,
    @Path('payment') required String payment,
  });

  /// Add a pre-ordered dish.
  ///
  /// `product_variant_id`, `quantity` (kg dishes in their steps: 1.5), `modifiers` [{modifier_id, quantity}],.
  /// `note`, `due_at` (when it must be ready; default the booking's start). Only while the booking is requested or.
  /// confirmed.
  @POST('/v1/pos/bookings/{uuid}/items')
  Future<PostV1PosBookingsUuidItemsResponse> bookingAddItem({
    @Path('uuid') required String uuid,
    @Body() required V1PosBookingsUuidItemsRequestBody body,
  });

  /// Remove a pre-ordered dish
  @DELETE('/v1/pos/bookings/{uuid}/items/{item}')
  Future<DeleteV1PosBookingsUuidItemsItemResponse> bookingRemoveItem({
    @Path('uuid') required String uuid,
    @Path('item') required String item,
  });

  /// Change a pre-ordered dish
  @PATCH('/v1/pos/bookings/{uuid}/items/{item}')
  Future<PatchV1PosBookingsUuidItemsItemResponse> bookingUpdateItem({
    @Path('uuid') required String uuid,
    @Path('item') required String item,
    @Body() V1PosBookingsUuidItemsItemRequestBody? body,
  });

  /// Mark a no-show.
  ///
  /// A confirmed booking whose start time has passed. Deposits: refund or retain afterwards.
  @POST('/v1/pos/bookings/{uuid}/no-show')
  Future<PostV1PosBookingsUuidNoShowResponse> bookingNoShow({
    @Path('uuid') required String uuid,
  });
}
