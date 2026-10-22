// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data177.dart';
import 'meta3.dart';

part 'get_v1_staff_orders_response.g.dart';

@JsonSerializable()
class GetV1StaffOrdersResponse {
  const GetV1StaffOrdersResponse({
    required this.success,
    required this.message,
    required this.data,
    required this.meta,
  });

  factory GetV1StaffOrdersResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1StaffOrdersResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data177> data;
  final Meta3 meta;

  Map<String, Object?> toJson() => _$GetV1StaffOrdersResponseToJson(this);
}
