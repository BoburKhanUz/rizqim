// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr_provider.dart';

part 'v1_pos_bills_uuid_qr_request_body.g.dart';

@JsonSerializable()
class V1PosBillsUuidQrRequestBody {
  const V1PosBillsUuidQrRequestBody({
    this.provider,
    this.amount,
    this.shiftUuid,
  });

  factory V1PosBillsUuidQrRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosBillsUuidQrRequestBodyFromJson(json);

  final QrProvider? provider;
  final num? amount;
  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;

  Map<String, Object?> toJson() => _$V1PosBillsUuidQrRequestBodyToJson(this);
}
