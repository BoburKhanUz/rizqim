// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account.dart';
import 'member.dart';
import 'usage.dart';

part 'data279.g.dart';

@JsonSerializable()
class Data279 {
  const Data279({
    required this.account,
    required this.member,
    required this.usage,
  });

  factory Data279.fromJson(Map<String, Object?> json) =>
      _$Data279FromJson(json);

  final Account account;
  final Member member;
  final Usage usage;

  Map<String, Object?> toJson() => _$Data279ToJson(this);
}
