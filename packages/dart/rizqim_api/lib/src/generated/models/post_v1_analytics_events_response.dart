// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data.dart';

part 'post_v1_analytics_events_response.g.dart';

@JsonSerializable()
class PostV1AnalyticsEventsResponse {
  const PostV1AnalyticsEventsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AnalyticsEventsResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1AnalyticsEventsResponseFromJson(json);

  final bool success;
  final String message;
  final Data data;

  Map<String, Object?> toJson() => _$PostV1AnalyticsEventsResponseToJson(this);
}
