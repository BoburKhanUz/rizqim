// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sets.dart';

part 'data113.g.dart';

@JsonSerializable()
class Data113 {
  const Data113({required this.sets});

  factory Data113.fromJson(Map<String, Object?> json) =>
      _$Data113FromJson(json);

  final List<Sets> sets;

  Map<String, Object?> toJson() => _$Data113ToJson(this);
}
