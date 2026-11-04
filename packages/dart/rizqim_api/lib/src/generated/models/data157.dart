// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings3.dart';

part 'data157.g.dart';

@JsonSerializable()
class Data157 {
  const Data157({required this.channels, required this.settings});

  factory Data157.fromJson(Map<String, Object?> json) =>
      _$Data157FromJson(json);

  final List<dynamic> channels;
  final List<Settings3> settings;

  Map<String, Object?> toJson() => _$Data157ToJson(this);
}
