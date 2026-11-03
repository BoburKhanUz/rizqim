// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'picks.g.dart';

@JsonSerializable()
class Picks {
  const Picks({required this.optionId, this.quantity, this.modifiers});

  factory Picks.fromJson(Map<String, Object?> json) => _$PicksFromJson(json);

  @JsonKey(name: 'option_id')
  final String optionId;
  final int? quantity;
  final List<String>? modifiers;

  Map<String, Object?> toJson() => _$PicksToJson(this);
}
