// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user.dart';
import 'store.dart';

part 'data94.g.dart';

@JsonSerializable()
class Data94 {
  const Data94({
    required this.user,
    required this.store,
    required this.smsVerificationRequired,
  });

  factory Data94.fromJson(Map<String, Object?> json) => _$Data94FromJson(json);

  final User user;
  final Store? store;
  @JsonKey(name: 'sms_verification_required')
  final bool smsVerificationRequired;

  Map<String, Object?> toJson() => _$Data94ToJson(this);
}
