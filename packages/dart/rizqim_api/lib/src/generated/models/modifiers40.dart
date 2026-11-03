// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modifiers40.g.dart';

@JsonSerializable()
class Modifiers40 {
  const Modifiers40({
    required this.name,
    required this.quantity,
    this.groupName,
    this.priceDelta,
  });

  factory Modifiers40.fromJson(Map<String, Object?> json) =>
      _$Modifiers40FromJson(json);

  final String name;
  @JsonKey(name: 'group_name')
  final String? groupName;
  final int quantity;
  @JsonKey(name: 'price_delta')
  final String? priceDelta;

  Map<String, Object?> toJson() => _$Modifiers40ToJson(this);
}
