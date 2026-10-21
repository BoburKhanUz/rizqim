// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'status5.dart';
import 'reason2.dart';

part 'v1_staff_orders_uuid_status_request_body.g.dart';

@JsonSerializable()
class V1StaffOrdersUuidStatusRequestBody {
  const V1StaffOrdersUuidStatusRequestBody({
    required this.status,
    this.reason,
    this.note,
  });

  factory V1StaffOrdersUuidStatusRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1StaffOrdersUuidStatusRequestBodyFromJson(json);

  final Status5 status;
  final Reason2? reason;
  final String? note;

  Map<String, Object?> toJson() =>
      _$V1StaffOrdersUuidStatusRequestBodyToJson(this);
}
