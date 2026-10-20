// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings.dart';

part 'data47.g.dart';

@JsonSerializable()
class Data47 {
  const Data47({required this.channels, required this.settings});

  factory Data47.fromJson(Map<String, Object?> json) => _$Data47FromJson(json);

  final List<dynamic> channels;
  final List<Settings> settings;

  Map<String, Object?> toJson() => _$Data47ToJson(this);
}
