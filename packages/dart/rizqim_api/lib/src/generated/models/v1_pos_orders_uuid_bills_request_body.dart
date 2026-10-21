// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'mode2.dart';
import 'bills3.dart';

part 'v1_pos_orders_uuid_bills_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidBillsRequestBody {
  const V1PosOrdersUuidBillsRequestBody({
    required this.version,
    required this.mode,
    this.parts,
    this.bills,
  });

  factory V1PosOrdersUuidBillsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosOrdersUuidBillsRequestBodyFromJson(json);

  final int version;
  final Mode2 mode;
  final int? parts;
  final List<Bills3>? bills;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidBillsRequestBodyToJson(this);
}
