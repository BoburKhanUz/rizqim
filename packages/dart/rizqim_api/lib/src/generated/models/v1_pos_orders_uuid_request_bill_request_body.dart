// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_request_bill_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidRequestBillRequestBody {
  const V1PosOrdersUuidRequestBillRequestBody({
    this.version,
    this.approvalToken,
  });

  factory V1PosOrdersUuidRequestBillRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidRequestBillRequestBodyFromJson(json);

  final int? version;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidRequestBillRequestBodyToJson(this);
}
