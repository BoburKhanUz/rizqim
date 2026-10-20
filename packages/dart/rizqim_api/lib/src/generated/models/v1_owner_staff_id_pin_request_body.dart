// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_staff_id_pin_request_body.g.dart';

@JsonSerializable()
class V1OwnerStaffIdPinRequestBody {
  const V1OwnerStaffIdPinRequestBody({required this.pin});

  factory V1OwnerStaffIdPinRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerStaffIdPinRequestBodyFromJson(json);

  final String pin;

  Map<String, Object?> toJson() => _$V1OwnerStaffIdPinRequestBodyToJson(this);
}
