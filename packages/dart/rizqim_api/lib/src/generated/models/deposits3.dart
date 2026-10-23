// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'taken3.dart';
import 'applied3.dart';
import 'refunded3.dart';

part 'deposits3.g.dart';

@JsonSerializable()
class Deposits3 {
  const Deposits3({
    required this.taken,
    required this.applied,
    required this.refunded,
  });

  factory Deposits3.fromJson(Map<String, Object?> json) =>
      _$Deposits3FromJson(json);

  final Taken3 taken;
  final Applied3 applied;
  final Refunded3 refunded;

  Map<String, Object?> toJson() => _$Deposits3ToJson(this);
}
