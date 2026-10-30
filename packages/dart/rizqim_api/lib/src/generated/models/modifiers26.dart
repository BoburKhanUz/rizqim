// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modifiers26.g.dart';

@JsonSerializable()
class Modifiers26 {
  const Modifiers26({
    required this.id,
    required this.name,
    required this.groupName,
    required this.quantity,
    required this.priceDelta,
  });

  factory Modifiers26.fromJson(Map<String, Object?> json) =>
      _$Modifiers26FromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'group_name')
  final String? groupName;
  final int quantity;
  @JsonKey(name: 'price_delta')
  final String priceDelta;

  Map<String, Object?> toJson() => _$Modifiers26ToJson(this);
}
