// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings5.dart';

part 'data195.g.dart';

@JsonSerializable()
class Data195 {
  const Data195({required this.channels, required this.settings});

  factory Data195.fromJson(Map<String, Object?> json) =>
      _$Data195FromJson(json);

  final List<dynamic> channels;
  final List<Settings5> settings;

  Map<String, Object?> toJson() => _$Data195ToJson(this);
}
