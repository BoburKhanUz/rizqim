// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_table_label_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidTableLabelRequestBody {
  const V1PosOrdersUuidTableLabelRequestBody({
    required this.version,
    required this.tableLabel,
    this.approvalToken,
  });

  factory V1PosOrdersUuidTableLabelRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidTableLabelRequestBodyFromJson(json);

  final int version;
  @JsonKey(name: 'table_label')
  final String? tableLabel;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidTableLabelRequestBodyToJson(this);
}
