// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings.dart';
import 'options4.dart';

part 'data135.g.dart';

@JsonSerializable()
class Data135 {
  const Data135({required this.settings, required this.options});

  factory Data135.fromJson(Map<String, Object?> json) =>
      _$Data135FromJson(json);

  final Settings settings;
  final Options4 options;

  Map<String, Object?> toJson() => _$Data135ToJson(this);
}
