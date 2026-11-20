// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings4.dart';
import 'options7.dart';

part 'data167.g.dart';

@JsonSerializable()
class Data167 {
  const Data167({required this.settings, required this.options});

  factory Data167.fromJson(Map<String, Object?> json) =>
      _$Data167FromJson(json);

  final Settings4 settings;
  final Options7 options;

  Map<String, Object?> toJson() => _$Data167ToJson(this);
}
