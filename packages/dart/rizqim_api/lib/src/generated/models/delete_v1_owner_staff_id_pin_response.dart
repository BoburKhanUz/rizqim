// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'delete_v1_owner_staff_id_pin_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerStaffIdPinResponse {
  const DeleteV1OwnerStaffIdPinResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerStaffIdPinResponse.fromJson(Map<String, Object?> json) =>
      _$DeleteV1OwnerStaffIdPinResponseFromJson(json);

  final bool success;
  final String message;
  final dynamic data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerStaffIdPinResponseToJson(this);
}
