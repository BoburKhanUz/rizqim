// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data241.dart';

part 'put_v1_pos_banquets_uuid_staff_response.g.dart';

@JsonSerializable()
class PutV1PosBanquetsUuidStaffResponse {
  const PutV1PosBanquetsUuidStaffResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1PosBanquetsUuidStaffResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1PosBanquetsUuidStaffResponseFromJson(json);

  final bool success;
  final String message;
  final Data241 data;

  Map<String, Object?> toJson() =>
      _$PutV1PosBanquetsUuidStaffResponseToJson(this);
}
