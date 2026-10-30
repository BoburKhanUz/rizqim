// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'options2.dart';

part 'slots2.g.dart';

@JsonSerializable()
class Slots2 {
  const Slots2({
    required this.id,
    required this.uuid,
    required this.name,
    required this.minPicks,
    required this.maxPicks,
    required this.isRequired,
    required this.allowModifiers,
    required this.options,
  });

  factory Slots2.fromJson(Map<String, Object?> json) => _$Slots2FromJson(json);

  final int id;
  final String uuid;
  final String name;
  @JsonKey(name: 'min_picks')
  final int minPicks;
  @JsonKey(name: 'max_picks')
  final int maxPicks;
  @JsonKey(name: 'is_required')
  final bool isRequired;
  @JsonKey(name: 'allow_modifiers')
  final bool allowModifiers;
  final List<Options2> options;

  Map<String, Object?> toJson() => _$Slots2ToJson(this);
}
