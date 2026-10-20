// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channels.dart';

part 'preferences.g.dart';

@JsonSerializable()
class Preferences {
  const Preferences({
    required this.type,
    required this.label,
    required this.description,
    required this.channels,
  });

  factory Preferences.fromJson(Map<String, Object?> json) =>
      _$PreferencesFromJson(json);

  final String type;
  final String label;
  final String description;
  final List<Channels> channels;

  Map<String, Object?> toJson() => _$PreferencesToJson(this);
}
