// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sets.dart';

part 'data95.g.dart';

@JsonSerializable()
class Data95 {
  const Data95({required this.sets});

  factory Data95.fromJson(Map<String, Object?> json) => _$Data95FromJson(json);

  final List<Sets> sets;

  Map<String, Object?> toJson() => _$Data95ToJson(this);
}
