// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/post_v1_analytics_events_response.dart';
import '../models/v1_analytics_events_request_body.dart';

part 'analytics_api.g.dart';

@RestApi()
abstract class AnalyticsApi {
  factory AnalyticsApi(Dio dio, {String? baseUrl}) = _AnalyticsApi;

  /// Send a batch of Mini App events.
  ///
  /// A signed-in customer's batch of 1-50 events of the session (`session_id`, a UUID the app makes at launch and.
  /// renews after 30 minutes without events). Each event: `uid` (UUID, the same on a retry), `name` (the list in.
  /// docs/API.md), `at` (client time, milliseconds since the epoch), the ids the event is about (`store_id`,.
  /// `product_id`, `category_id`, integers) and its allowlisted `props`. Answers 202 with how many events were.
  /// accepted and dropped, never which: an event off the allowlist, older than 24 hours, an `order_placed` of an.
  /// order that is not the caller's recent (24 h) customer order or that already has one, and every batch of a.
  /// session past the user's daily session cap are dropped; staff and RIZQIM team accounts' events are all dropped. A replayed.
  /// event (same `session_id` and `uid` from the same user) is accepted and not stored twice. 422 only for a malformed.
  /// envelope (no or a nil `session_id`, no `platform`, unknown top-level field, more than 50 events), 413 for a body.
  /// over 32 KB (nginx refuses it before PHP), 429 over a limit (`Retry-After`).
  /// Nothing identifying is stored beyond the token's user id: no IP address, User-Agent, raw start_param or text.
  @POST('/v1/analytics/events')
  Future<PostV1AnalyticsEventsResponse> eventStore({
    @Body() required V1AnalyticsEventsRequestBody body,
  });
}
