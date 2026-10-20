// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'status2.dart';
import 'reason2.dart';

part 'v1_orders_uuid_status_request_body.g.dart';

@JsonSerializable()
class V1OrdersUuidStatusRequestBody {
  const V1OrdersUuidStatusRequestBody({
    required this.status,
    this.courierId,
    this.reason,
    this.note,
  });

  factory V1OrdersUuidStatusRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OrdersUuidStatusRequestBodyFromJson(json);

  final Status2 status;
  @JsonKey(name: 'courier_id')
  final int? courierId;
  final Reason2? reason;
  final String? note;

  Map<String, Object?> toJson() => _$V1OrdersUuidStatusRequestBodyToJson(this);
}
