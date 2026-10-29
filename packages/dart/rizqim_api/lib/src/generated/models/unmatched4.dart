// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bank4.dart';
import 'rizqim4.dart';

part 'unmatched4.g.dart';

@JsonSerializable()
class Unmatched4 {
  const Unmatched4({required this.bank, required this.rizqim});

  factory Unmatched4.fromJson(Map<String, Object?> json) =>
      _$Unmatched4FromJson(json);

  final List<Bank4> bank;
  final List<Rizqim4> rizqim;

  Map<String, Object?> toJson() => _$Unmatched4ToJson(this);
}
