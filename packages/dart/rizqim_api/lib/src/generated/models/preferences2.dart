// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channels2.dart';

part 'preferences2.g.dart';

@JsonSerializable()
class Preferences2 {
  const Preferences2({
    required this.type,
    required this.label,
    required this.description,
    required this.channels,
  });

  factory Preferences2.fromJson(Map<String, Object?> json) =>
      _$Preferences2FromJson(json);

  final String type;
  final String label;
  final String description;
  final List<Channels2> channels;

  Map<String, Object?> toJson() => _$Preferences2ToJson(this);
}
