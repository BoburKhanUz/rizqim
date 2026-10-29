// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data147.dart';

part 'put_v1_owner_staff_pay_settings_response.g.dart';

@JsonSerializable()
class PutV1OwnerStaffPaySettingsResponse {
  const PutV1OwnerStaffPaySettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerStaffPaySettingsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerStaffPaySettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data147 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerStaffPaySettingsResponseToJson(this);
}
