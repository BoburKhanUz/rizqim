// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_display_queue_response.dart';
import '../models/get_v1_pos_display_state_response.dart';
import '../models/post_v1_pos_display_cart_response.dart';
import '../models/v1_pos_display_cart_request_body.dart';

part 'pos_customer_display_api.g.dart';

@RestApi()
abstract class PosCustomerDisplayApi {
  factory PosCustomerDisplayApi(Dio dio, {String? baseUrl}) =
      _PosCustomerDisplayApi;

  /// Publish the cart for the second screen.
  ///
  /// The till tells its customer display what to show: `state` (`idle` empty/welcome, `cart` lines being added,.
  /// `payment` the amount due and optionally a payment QR, `paid` "Rahmat"), the lines, the totals as decimal strings, the.
  /// queue number if the order has one and, in `payment`, the tender and a QR (`payment.qr.payload` is the text the display.
  /// draws as a QR code). `seq` grows with every publish (a late older one is dropped); `session` names the till's app run,.
  /// so a restarted till (seq from 1) is not mistaken for a stale one. Answers 200 `stored: false` for a stale publish.
  /// Fire-and-forget for the till: it never waits for, or fails because of, the display. A POS terminal's own token;.
  /// needs `customer_display`; the display shows it within a moment (`display.cart` on `pos-branch.{id}`).
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/pos/display/cart')
  Future<PostV1PosDisplayCartResponse> posDisplayPublish({
    @Body() required V1PosDisplayCartRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// The pickup board for the display.
  ///
  /// Today's "Tayyorlanmoqda / Tayyor" numbers of the display's branch, the same payload as GET /pos/queue (a terminal's own.
  /// token instead of a staff session). Refetch on `queue.*` events of `pos-branch.{id}`. Needs `queue_calling`.
  @GET('/v1/pos/display/queue')
  Future<GetV1PosDisplayQueueResponse> posDisplayQueue();

  /// The display's state.
  ///
  /// What a customer display (or a poll after a missed event) needs: its branch, the till it follows (`till_uuid`, null:.
  /// the branch's latest cart), which screens the format allows (`modules`) and the current `cart` (null: nothing.
  /// published yet or the cart expired). The display refetches this on `display.cart` events of `pos-branch.{id}`.
  /// A terminal's own token; 422 `branch_required` without a branch.
  @GET('/v1/pos/display/state')
  Future<GetV1PosDisplayStateResponse> posDisplayState();
}
