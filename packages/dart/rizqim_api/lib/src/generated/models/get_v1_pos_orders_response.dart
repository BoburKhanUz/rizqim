// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data171.dart';

part 'get_v1_pos_orders_response.g.dart';

@JsonSerializable()
class GetV1PosOrdersResponse {
  const GetV1PosOrdersResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosOrdersResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosOrdersResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data171> data;

  Map<String, Object?> toJson() => _$GetV1PosOrdersResponseToJson(this);
}
