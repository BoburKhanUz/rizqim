// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qrs.dart';

part 'data259.g.dart';

@JsonSerializable()
class Data259 {
  const Data259({required this.qrs});

  factory Data259.fromJson(Map<String, Object?> json) =>
      _$Data259FromJson(json);

  final List<Qrs> qrs;

  Map<String, Object?> toJson() => _$Data259ToJson(this);
}
