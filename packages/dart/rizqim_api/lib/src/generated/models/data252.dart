// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qrs.dart';

part 'data252.g.dart';

@JsonSerializable()
class Data252 {
  const Data252({required this.qrs});

  factory Data252.fromJson(Map<String, Object?> json) =>
      _$Data252FromJson(json);

  final List<Qrs> qrs;

  Map<String, Object?> toJson() => _$Data252ToJson(this);
}
