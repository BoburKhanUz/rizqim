// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modifiers9.g.dart';

@JsonSerializable()
class Modifiers9 {
  const Modifiers9({required this.modifierId, this.quantity});

  factory Modifiers9.fromJson(Map<String, Object?> json) =>
      _$Modifiers9FromJson(json);

  @JsonKey(name: 'modifier_id')
  final int modifierId;
  final int? quantity;

  Map<String, Object?> toJson() => _$Modifiers9ToJson(this);
}
