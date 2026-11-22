// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tips.dart';

part 'data168.g.dart';

@JsonSerializable()
class Data168 {
  const Data168({required this.tips, required this.truncated});

  factory Data168.fromJson(Map<String, Object?> json) =>
      _$Data168FromJson(json);

  final List<Tips> tips;
  final bool truncated;

  Map<String, Object?> toJson() => _$Data168ToJson(this);
}
