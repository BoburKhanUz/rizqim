// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data172.dart';

part 'get_v1_owner_staff_pay_work_sessions_response.g.dart';

@JsonSerializable()
class GetV1OwnerStaffPayWorkSessionsResponse {
  const GetV1OwnerStaffPayWorkSessionsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerStaffPayWorkSessionsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerStaffPayWorkSessionsResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data172> data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerStaffPayWorkSessionsResponseToJson(this);
}
