// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bank.dart';
import 'rizqim.dart';

part 'unmatched.g.dart';

@JsonSerializable()
class Unmatched {
  const Unmatched({required this.bank, required this.rizqim});

  factory Unmatched.fromJson(Map<String, Object?> json) =>
      _$UnmatchedFromJson(json);

  final List<Bank> bank;
  final List<Rizqim> rizqim;

  Map<String, Object?> toJson() => _$UnmatchedToJson(this);
}
