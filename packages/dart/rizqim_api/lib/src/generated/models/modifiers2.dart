// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modifiers2.g.dart';

@JsonSerializable()
class Modifiers2 {
  const Modifiers2({
    required this.id,
    required this.uuid,
    required this.groupId,
    required this.groupName,
    required this.name,
    required this.quantity,
    required this.priceDelta,
  });

  factory Modifiers2.fromJson(Map<String, Object?> json) =>
      _$Modifiers2FromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'group_id')
  final int groupId;
  @JsonKey(name: 'group_name')
  final String? groupName;
  final String name;
  final int quantity;
  @JsonKey(name: 'price_delta')
  final String priceDelta;

  Map<String, Object?> toJson() => _$Modifiers2ToJson(this);
}
