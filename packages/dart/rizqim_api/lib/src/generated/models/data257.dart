// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qrs.dart';

part 'data257.g.dart';

@JsonSerializable()
class Data257 {
  const Data257({required this.qrs});

  factory Data257.fromJson(Map<String, Object?> json) =>
      _$Data257FromJson(json);

  final List<Qrs> qrs;

  Map<String, Object?> toJson() => _$Data257ToJson(this);
}
