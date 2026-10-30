// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sets.dart';

part 'data112.g.dart';

@JsonSerializable()
class Data112 {
  const Data112({required this.sets});

  factory Data112.fromJson(Map<String, Object?> json) =>
      _$Data112FromJson(json);

  final List<Sets> sets;

  Map<String, Object?> toJson() => _$Data112ToJson(this);
}
