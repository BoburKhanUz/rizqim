// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings4.dart';
import 'options7.dart';

part 'data165.g.dart';

@JsonSerializable()
class Data165 {
  const Data165({required this.settings, required this.options});

  factory Data165.fromJson(Map<String, Object?> json) =>
      _$Data165FromJson(json);

  final Settings4 settings;
  final Options7 options;

  Map<String, Object?> toJson() => _$Data165ToJson(this);
}
