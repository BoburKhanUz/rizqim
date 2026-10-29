// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'card_modes.g.dart';

@JsonSerializable()
class CardModes {
  const CardModes({required this.value, required this.label});

  factory CardModes.fromJson(Map<String, Object?> json) =>
      _$CardModesFromJson(json);

  final String value;
  final String label;

  Map<String, Object?> toJson() => _$CardModesToJson(this);
}
