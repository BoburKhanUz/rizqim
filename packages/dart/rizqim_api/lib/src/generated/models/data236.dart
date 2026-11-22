// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event2.dart';
import 'settlement2.dart';

part 'data236.g.dart';

@JsonSerializable()
class Data236 {
  const Data236({required this.event, required this.settlement});

  factory Data236.fromJson(Map<String, Object?> json) =>
      _$Data236FromJson(json);

  final Event2 event;
  final Settlement2 settlement;

  Map<String, Object?> toJson() => _$Data236ToJson(this);
}
