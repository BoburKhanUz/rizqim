// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'taken.dart';
import 'applied.dart';
import 'refunded.dart';

part 'deposits.g.dart';

@JsonSerializable()
class Deposits {
  const Deposits({
    required this.taken,
    required this.applied,
    required this.refunded,
  });

  factory Deposits.fromJson(Map<String, Object?> json) =>
      _$DepositsFromJson(json);

  final Taken taken;
  final Applied applied;
  final Refunded refunded;

  Map<String, Object?> toJson() => _$DepositsToJson(this);
}
