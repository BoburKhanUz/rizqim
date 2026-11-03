// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modifiers39.g.dart';

@JsonSerializable()
class Modifiers39 {
  const Modifiers39({
    required this.name,
    required this.quantity,
    this.groupName,
    this.priceDelta,
  });

  factory Modifiers39.fromJson(Map<String, Object?> json) =>
      _$Modifiers39FromJson(json);

  final String name;
  @JsonKey(name: 'group_name')
  final String? groupName;
  final int quantity;
  @JsonKey(name: 'price_delta')
  final String? priceDelta;

  Map<String, Object?> toJson() => _$Modifiers39ToJson(this);
}
