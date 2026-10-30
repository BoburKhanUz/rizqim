// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'order_types2.g.dart';

@JsonSerializable()
class OrderTypes2 {
  const OrderTypes2({required this.key, required this.label});

  factory OrderTypes2.fromJson(Map<String, Object?> json) =>
      _$OrderTypes2FromJson(json);

  final String key;
  final String label;

  Map<String, Object?> toJson() => _$OrderTypes2ToJson(this);
}
