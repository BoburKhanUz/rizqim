// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'order13.g.dart';

@JsonSerializable()
class Order13 {
  const Order13({
    required this.uuid,
    required this.localNumber,
    required this.shortCode,
    required this.tableRef,
    required this.status,
  });

  factory Order13.fromJson(Map<String, Object?> json) =>
      _$Order13FromJson(json);

  final String uuid;
  @JsonKey(name: 'local_number')
  final String? localNumber;
  @JsonKey(name: 'short_code')
  final String? shortCode;
  @JsonKey(name: 'table_ref')
  final String? tableRef;
  final String status;

  Map<String, Object?> toJson() => _$Order13ToJson(this);
}
