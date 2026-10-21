// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_charges_charge_restore_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidChargesChargeRestoreRequestBody {
  const V1PosOrdersUuidChargesChargeRestoreRequestBody({
    required this.version,
    this.approvalToken,
  });

  factory V1PosOrdersUuidChargesChargeRestoreRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidChargesChargeRestoreRequestBodyFromJson(json);

  final int version;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidChargesChargeRestoreRequestBodyToJson(this);
}
