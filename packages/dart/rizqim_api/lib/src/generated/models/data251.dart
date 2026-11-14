// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qrs.dart';

part 'data251.g.dart';

@JsonSerializable()
class Data251 {
  const Data251({required this.qrs});

  factory Data251.fromJson(Map<String, Object?> json) =>
      _$Data251FromJson(json);

  final List<Qrs> qrs;

  Map<String, Object?> toJson() => _$Data251ToJson(this);
}
