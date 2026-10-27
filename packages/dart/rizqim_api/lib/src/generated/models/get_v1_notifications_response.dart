// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data22.dart';

part 'get_v1_notifications_response.g.dart';

@JsonSerializable()
class GetV1NotificationsResponse {
  const GetV1NotificationsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1NotificationsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1NotificationsResponseFromJson(json);

  final bool success;
  final String message;
  final Data22 data;

  Map<String, Object?> toJson() => _$GetV1NotificationsResponseToJson(this);
}
