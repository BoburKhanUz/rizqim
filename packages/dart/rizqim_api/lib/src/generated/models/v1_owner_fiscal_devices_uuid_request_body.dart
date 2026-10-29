// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_fiscal_devices_uuid_request_body.g.dart';

@JsonSerializable()
class V1OwnerFiscalDevicesUuidRequestBody {
  const V1OwnerFiscalDevicesUuidRequestBody({
    this.smartKassa,
    this.fiscalKassaId,
  });

  factory V1OwnerFiscalDevicesUuidRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerFiscalDevicesUuidRequestBodyFromJson(json);

  @JsonKey(name: 'smart_kassa')
  final bool? smartKassa;
  @JsonKey(name: 'fiscal_kassa_id')
  final String? fiscalKassaId;

  Map<String, Object?> toJson() =>
      _$V1OwnerFiscalDevicesUuidRequestBodyToJson(this);
}
