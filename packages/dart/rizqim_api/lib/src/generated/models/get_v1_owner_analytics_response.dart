// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data23.dart';

part 'get_v1_owner_analytics_response.g.dart';

@JsonSerializable()
class GetV1OwnerAnalyticsResponse {
  const GetV1OwnerAnalyticsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerAnalyticsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerAnalyticsResponseFromJson(json);

  final bool success;
  final String message;
  final Data23 data;

  Map<String, Object?> toJson() => _$GetV1OwnerAnalyticsResponseToJson(this);
}
