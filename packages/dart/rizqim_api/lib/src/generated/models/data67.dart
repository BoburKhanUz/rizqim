// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings.dart';

part 'data67.g.dart';

@JsonSerializable()
class Data67 {
  const Data67({required this.channels, required this.settings});

  factory Data67.fromJson(Map<String, Object?> json) => _$Data67FromJson(json);

  final List<dynamic> channels;
  final List<Settings> settings;

  Map<String, Object?> toJson() => _$Data67ToJson(this);
}
