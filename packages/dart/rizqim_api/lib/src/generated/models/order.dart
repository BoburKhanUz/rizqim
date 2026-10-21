// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'order.g.dart';

@JsonSerializable()
class Order {
  const Order({required this.uuid, required this.shortCode});

  factory Order.fromJson(Map<String, Object?> json) => _$OrderFromJson(json);

  final String uuid;
  @JsonKey(name: 'short_code')
  final String? shortCode;

  Map<String, Object?> toJson() => _$OrderToJson(this);
}
