// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account.dart';
import 'entries2.dart';

part 'data144.g.dart';

@JsonSerializable()
class Data144 {
  const Data144({required this.account, required this.entries});

  factory Data144.fromJson(Map<String, Object?> json) =>
      _$Data144FromJson(json);

  final Account account;
  final List<Entries2> entries;

  Map<String, Object?> toJson() => _$Data144ToJson(this);
}
