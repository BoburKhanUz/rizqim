// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'order_types.g.dart';

@JsonSerializable()
class OrderTypes {
  const OrderTypes({required this.key, required this.label});

  factory OrderTypes.fromJson(Map<String, Object?> json) =>
      _$OrderTypesFromJson(json);

  final String key;
  final String label;

  Map<String, Object?> toJson() => _$OrderTypesToJson(this);
}
