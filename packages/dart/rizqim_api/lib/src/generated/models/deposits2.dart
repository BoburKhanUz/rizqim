// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'taken2.dart';
import 'applied2.dart';
import 'refunded2.dart';

part 'deposits2.g.dart';

@JsonSerializable()
class Deposits2 {
  const Deposits2({
    required this.taken,
    required this.applied,
    required this.refunded,
  });

  factory Deposits2.fromJson(Map<String, Object?> json) =>
      _$Deposits2FromJson(json);

  final Taken2 taken;
  final Applied2 applied;
  final Refunded2 refunded;

  Map<String, Object?> toJson() => _$Deposits2ToJson(this);
}
