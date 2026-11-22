// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'members2.dart';

part 'data292.g.dart';

@JsonSerializable()
class Data292 {
  const Data292({required this.created, required this.members});

  factory Data292.fromJson(Map<String, Object?> json) =>
      _$Data292FromJson(json);

  final int created;
  final List<Members2> members;

  Map<String, Object?> toJson() => _$Data292ToJson(this);
}
