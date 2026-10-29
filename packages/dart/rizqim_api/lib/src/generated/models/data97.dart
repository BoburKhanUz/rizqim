// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sets.dart';

part 'data97.g.dart';

@JsonSerializable()
class Data97 {
  const Data97({required this.sets});

  factory Data97.fromJson(Map<String, Object?> json) => _$Data97FromJson(json);

  final List<Sets> sets;

  Map<String, Object?> toJson() => _$Data97ToJson(this);
}
