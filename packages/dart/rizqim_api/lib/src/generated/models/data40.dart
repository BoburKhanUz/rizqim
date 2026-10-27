// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'preferences.dart';

part 'data40.g.dart';

@JsonSerializable()
class Data40 {
  const Data40({required this.preferences});

  factory Data40.fromJson(Map<String, Object?> json) => _$Data40FromJson(json);

  final List<Preferences> preferences;

  Map<String, Object?> toJson() => _$Data40ToJson(this);
}
