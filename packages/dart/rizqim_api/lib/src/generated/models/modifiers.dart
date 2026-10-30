// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modifiers.g.dart';

@JsonSerializable()
class Modifiers {
  const Modifiers({
    required this.id,
    required this.uuid,
    required this.groupId,
    required this.groupName,
    required this.name,
    required this.quantity,
    required this.priceDelta,
  });

  factory Modifiers.fromJson(Map<String, Object?> json) =>
      _$ModifiersFromJson(json);

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

  Map<String, Object?> toJson() => _$ModifiersToJson(this);
}
