// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'events.dart';

part 'v1_analytics_events_request_body.g.dart';

@JsonSerializable()
class V1AnalyticsEventsRequestBody {
  const V1AnalyticsEventsRequestBody({
    required this.sessionId,
    required this.platform,
    required this.events,
    this.appVersion,
    this.startParam,
  });

  factory V1AnalyticsEventsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1AnalyticsEventsRequestBodyFromJson(json);

  /// The Mini App session (UUID).
  @JsonKey(name: 'session_id')
  final String sessionId;

  /// Telegram.WebApp.platform (ios, android, tdesktop, macos, web, weba...; anything else is stored as unknown).
  final String platform;

  /// The Mini App build, e.g. 1.4.2 (at most 16 of [0-9A-Za-z._+-], otherwise not kept).
  @JsonKey(name: 'app_version')
  final String? appVersion;

  /// Telegram's start_param of this launch: only its kind is kept (campaign, referral, store, invite, none, unknown).
  @JsonKey(name: 'start_param')
  final String? startParam;

  /// 1-50 events: {uid, name, at, store_id?, product_id?, category_id?, props?}.
  final List<Events> events;

  Map<String, Object?> toJson() => _$V1AnalyticsEventsRequestBodyToJson(this);
}
