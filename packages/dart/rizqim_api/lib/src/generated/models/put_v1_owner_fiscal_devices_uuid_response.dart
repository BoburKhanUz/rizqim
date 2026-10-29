// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data77.dart';

part 'put_v1_owner_fiscal_devices_uuid_response.g.dart';

@JsonSerializable()
class PutV1OwnerFiscalDevicesUuidResponse {
  const PutV1OwnerFiscalDevicesUuidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerFiscalDevicesUuidResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerFiscalDevicesUuidResponseFromJson(json);

  final bool success;
  final String message;
  final Data77 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerFiscalDevicesUuidResponseToJson(this);
}
