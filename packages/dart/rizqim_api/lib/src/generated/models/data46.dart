// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings.dart';

part 'data46.g.dart';

@JsonSerializable()
class Data46 {
  const Data46({required this.channels, required this.settings});

  factory Data46.fromJson(Map<String, Object?> json) => _$Data46FromJson(json);

  final List<dynamic> channels;
  final List<Settings> settings;

  Map<String, Object?> toJson() => _$Data46ToJson(this);
}
